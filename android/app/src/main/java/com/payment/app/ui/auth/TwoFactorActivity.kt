package com.payment.app.ui.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.payment.app.databinding.ActivityTwoFactorBinding
import com.payment.app.ui.main.MainActivity
import com.payment.app.util.Resource
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class TwoFactorActivity : AppCompatActivity() {

    private lateinit var binding: ActivityTwoFactorBinding
    private val viewModel: TwoFactorViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityTwoFactorBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupViews()
        observeState()
    }

    private fun setupViews() {
        binding.btnVerify.setOnClickListener {
            val code = binding.etCode.text.toString().trim()
            viewModel.verify(code)
        }
    }

    private fun observeState() {
        viewModel.verifyState.observe(this) { state ->
            when (state) {
                is Resource.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                    binding.btnVerify.isEnabled = false
                }
                is Resource.Success -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnVerify.isEnabled = true

                    Toast.makeText(this, state.data, Toast.LENGTH_SHORT).show()
                    startActivity(Intent(this, MainActivity::class.java))
                    finish()
                }
                is Resource.Error -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnVerify.isEnabled = true

                    if (state.message?.contains("terblokir", ignoreCase = true) == true) {
                        Toast.makeText(this, state.message, Toast.LENGTH_LONG).show()
                        finish()
                    } else {
                        Toast.makeText(this, state.message, Toast.LENGTH_SHORT).show()
                        binding.etCode.text?.clear()
                    }
                }
            }
        }
    }
}
