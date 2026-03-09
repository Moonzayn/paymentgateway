package com.payment.app.ui.pos

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.payment.app.data.model.PosItem
import com.payment.app.databinding.ItemPosProductBinding
import com.payment.app.databinding.ItemPosCartBinding
import java.text.NumberFormat
import java.util.Locale

class PosProductAdapter(
    private val onItemClick: (PosItem) -> Unit
) : ListAdapter<PosItem, PosProductAdapter.ViewHolder>(DiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemPosProductBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ViewHolder(private val binding: ItemPosProductBinding) :
        RecyclerView.ViewHolder(binding.root) {

        init {
            binding.root.setOnClickListener {
                val position = adapterPosition
                if (position != RecyclerView.NO_POSITION) {
                    val item = getItem(position)
                    onItemClick(item.copy(qty = 1))
                }
            }
        }

        fun bind(item: PosItem) {
            binding.tvNama.text = item.nama
            binding.tvHarga.text = formatRupiah(item.harga)
        }
    }
}

class PosCartAdapter(
    private val onQuantityChanged: (Int, Int) -> Unit,
    private val onRemove: (Int) -> Unit
) : ListAdapter<PosItem, PosCartAdapter.ViewHolder>(DiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemPosCartBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position), position)
    }

    inner class ViewHolder(private val binding: ItemPosCartBinding) :
        RecyclerView.ViewHolder(binding.root) {

        fun bind(item: PosItem, position: Int) {
            binding.tvNama.text = item.nama
            binding.tvHarga.text = formatRupiah(item.harga)
            binding.tvQty.text = item.qty.toString()
            binding.tvSubtotal.text = formatRupiah(item.harga * item.qty)

            binding.btnMinus.setOnClickListener {
                onQuantityChanged(position, item.qty - 1)
            }

            binding.btnPlus.setOnClickListener {
                onQuantityChanged(position, item.qty + 1)
            }

            binding.btnRemove.setOnClickListener {
                onRemove(position)
            }
        }
    }
}

class DiffCallback : DiffUtil.ItemCallback<PosItem>() {
    override fun areItemsTheSame(oldItem: PosItem, newItem: PosItem): Boolean {
        return oldItem.id == newItem.id
    }

    override fun areContentsTheSame(oldItem: PosItem, newItem: PosItem): Boolean {
        return oldItem == newItem
    }
}

fun formatRupiah(amount: Int): String {
    return "Rp ${String.format("%,d", amount).replace(",", ".")}"
}
