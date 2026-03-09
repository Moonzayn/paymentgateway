package com.payment.app.ui.deposit

import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.payment.app.databinding.ActivityDepositBinding
import com.payment.app.util.Resource
import dagger.hilt.android.AndroidEntryPoint
import java.io.File

@AndroidEntryPoint
class DepositActivity : AppCompatActivity() {

    private lateinit var binding: ActivityDepositBinding
    private val viewModel: DepositViewModel by viewModels()
    private var selectedFile: File? = null
    private var selectedMetode: String = "bank_transfer"

    private val metodeItems = listOf(
        "Bank Transfer" to "bank_transfer",
        "BCA" to "bca",
        "BNI" to "bni",
        "BRI" to "bri",
        "Mandiri" to "mandiri",
        "DANA" to "dana",
        "OVO" to "ovo",
        "GoPay" to "gopay",
        "ShopeePay" to "shopee"
    )

    private val pickImage = registerForActivityResult(ActivityResultContracts.GetContent()) { uri: Uri? ->
        uri?.let {
            selectedFile = File(cacheDir, "bukti_${System.currentTimeMillis()}.jpg")
            contentResolver.openInputStream(uri)?.use { input ->
                selectedFile?.outputStream()?.use { output ->
                    input.copyTo(output)
                }
            }
            binding.tvFileName.text = selectedFile?.name ?: "File dipilih"
            binding.tvFileName.visibility = View.VISIBLE
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityDepositBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.title = "Deposit"

        setupViews()
        observeData()
    }

    private fun setupViews() {
        // Setup metode pembayaran spinner
        val adapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_dropdown_item,
            metodeItems.map { it.first }
        )
        binding.spinnerMetode.adapter = adapter

        binding.btnPilihFile.setOnClickListener {
            pickImage.launch("image/*")
        }

        binding.btnKirim.setOnClickListener {
            val nominalStr = binding.etNominal.text.toString()
            val nominal = nominalStr.toDoubleOrNull()

            if (nominal == null || nominal < 10000) {
                Toast.makeText(this, "Minimal deposit Rp 10.000", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            val selectedPosition = binding.spinnerMetode.selectedItemPosition
            selectedMetode = metodeItems[selectedPosition].second

            viewModel.submitDeposit(nominal, selectedMetode, selectedFile)
        }
    }

    private fun observeData() {
        viewModel.depositState.observe(this) { state ->
            when (state) {
                is Resource.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                    binding.btnKirim.isEnabled = false
                }
                is Resource.Success -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnKirim.isEnabled = true
                    Toast.makeText(this, state.data, Toast.LENGTH_SHORT).show()
                    finish()
                }
                is Resource.Error -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnKirim.isEnabled = true
                    Toast.makeText(this, state.message, Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressedDispatcher.onBackPressed()
        return true
    }
}
